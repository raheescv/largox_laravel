<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <div
    data-slot="card"
    :class="
      cn(
        'bg-card/95 text-card-foreground flex flex-col gap-6 rounded-lg border py-6 shadow-sm backdrop-blur',
        props.class,
      )
    "
  >
    <slot />
  </div>
</template>
